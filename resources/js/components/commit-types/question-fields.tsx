/**
 * A question has nothing to fill in next to its body: this only tells its author what happens next.
 */
export function QuestionFields() {
    return (
        <p
            className="mt-2 text-sm text-muted-foreground"
            data-test="question-fields"
        >
            Once replies come in, you can accept one as the answer.
        </p>
    );
}
